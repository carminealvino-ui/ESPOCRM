define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

  var FIELDS = [
    'finanziamento',
    'statoFinanziamento',
    'tassoZero',
    'importoFinanziato',
    'rataPrestito',
    'nrRate',
  ];

  return Dep.extend({

    // Due righe multi-colonna, ma ogni campo usa {{{var ...Field this}}} come il pannello
    // standard a colonna singola: così Espo inserisce le view con i valori.
    templateContent: [
      '<div class="row">',
      '  <div class="col-sm-6 cell form-group" data-name="finanziamento">',
      '    <label class="control-label" data-name="finanziamento">',
      '      <span class="label-text">{{labels.finanziamento}}</span>',
      '    </label>',
      '    <div class="field" data-name="finanziamento">{{{var finanziamentoField this}}}</div>',
      '  </div>',
      '  <div class="col-sm-6 cell form-group" data-name="statoFinanziamento">',
      '    <label class="control-label" data-name="statoFinanziamento">',
      '      <span class="label-text">{{labels.statoFinanziamento}}</span>',
      '    </label>',
      '    <div class="field" data-name="statoFinanziamento">{{{var statoFinanziamentoField this}}}</div>',
      '  </div>',
      '</div>',
      '<div class="row">',
      '  <div class="col-sm-3 cell form-group" data-name="tassoZero">',
      '    <label class="control-label" data-name="tassoZero">',
      '      <span class="label-text">{{labels.tassoZero}}</span>',
      '    </label>',
      '    <div class="field" data-name="tassoZero">{{{var tassoZeroField this}}}</div>',
      '  </div>',
      '  <div class="col-sm-3 cell form-group" data-name="importoFinanziato">',
      '    <label class="control-label" data-name="importoFinanziato">',
      '      <span class="label-text">{{labels.importoFinanziato}}</span>',
      '    </label>',
      '    <div class="field" data-name="importoFinanziato">{{{var importoFinanziatoField this}}}</div>',
      '  </div>',
      '  <div class="col-sm-3 cell form-group" data-name="rataPrestito">',
      '    <label class="control-label" data-name="rataPrestito">',
      '      <span class="label-text">{{labels.rataPrestito}}</span>',
      '    </label>',
      '    <div class="field" data-name="rataPrestito">{{{var rataPrestitoField this}}}</div>',
      '  </div>',
      '  <div class="col-sm-3 cell form-group" data-name="nrRate">',
      '    <label class="control-label" data-name="nrRate">',
      '      <span class="label-text">{{labels.nrRate}}</span>',
      '    </label>',
      '    <div class="field" data-name="nrRate">{{{var nrRateField this}}}</div>',
      '  </div>',
      '</div>',
    ].join('\n'),

    setupFields: function () {
      this.fieldList = FIELDS.slice();
    },

    data: function () {
      var scope = this.model.entityType;
      var labels = {};

      FIELDS.forEach(function (field) {
        labels[field] = this.translate(field, 'fields', scope);
      }, this);

      return {
        labels: labels,
        entityType: scope,
      };
    },
  });
});
