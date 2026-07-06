define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

    return Dep.extend({

        // Template standard Espo (record/panels/side): {{{var viewKey ../this}}} funziona.
        // Il layout a 2 righe è gestito via CSS in custom-ui.css (.panel-finanziamento).
        setupFields: function () {
            this.fieldList = [
                'finanziamento',
                'statoFinanziamento',
                'tassoZero',
                'importoFinanziato',
                'rataPrestito',
                'nrRate',
            ];
        },
    });
});
