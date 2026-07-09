/* global define */

define('custom:views/provvigione/record/edit', ['views/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setFieldReadOnly('importo');
            this.setFieldReadOnly('importoConsolidato');
            this.setFieldReadOnly('importoBaseCalcolo');
            this.setFieldReadOnly('baseCalcolo');
            this.setFieldReadOnly('tassoProvvigioni');
            this.setFieldReadOnly('regolaProvvigionale');
            this.setFieldReadOnly('regimeProvvigione');
            this.setFieldReadOnly('statoProvvigione');
            this.setFieldReadOnly('tipo');
        }
    });
});
