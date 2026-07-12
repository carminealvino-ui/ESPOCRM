define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

    return Dep.extend({

        setupFields: function () {
            this.fieldList = [
                'finanziamento',
                'statoFinanziamento',
                'importoSaldo',
                'tassoZero',
                'importoFinanziato',
                'rataPrestito',
                'nrRate',
            ];
        },
    });
});
