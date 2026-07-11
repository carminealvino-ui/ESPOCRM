define('custom:views/quote/record/panels/finanziamento', ['views/record/panels/bottom'], function (Dep) {

    return Dep.extend({

        setupFields: function () {
            this.fieldList = [
                'finanziamento',
                'statoFinanziamento',
                'importoCaparra',
                'importoSaldo',
                'tassoZero',
                'shippingCost',
                'importoFinanziato',
                'rataPrestito',
                'nrRate',
            ];
        },
    });
});
