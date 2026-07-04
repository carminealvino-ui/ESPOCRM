/* global define */

define('custom:views/provvigione/record/edit', ['views/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setFieldReadOnly('importo');
            this.setFieldReadOnly('importoConsolidato');
            this.setFieldReadOnly('importoPrevisto');
            this.setFieldReadOnly('tassoProvvigioni');
            this.setFieldReadOnly('regolaProvvigionale');
            this.setFieldReadOnly('regimeProvvigione');
            this.setFieldReadOnly('scostamentoImporto');
            this.setFieldReadOnly('dataLiquidazionePrevista');
            this.setFieldReadOnly('giorniLiquidazioneDaAttivazione');
        }
    });
});
