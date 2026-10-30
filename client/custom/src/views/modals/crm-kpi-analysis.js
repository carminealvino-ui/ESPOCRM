define('custom:views/modals/crm-kpi-analysis', ['views/modal'], function (Dep) {
    return Dep.extend({
        template: 'custom:modals/crm-kpi-analysis',
        className: 'dialog dialog-record',
        setup: function () {
            Dep.prototype.setup.call(this);
            this.headerText = this.options.title;
            this.buttonList = [{name: 'close', label: 'Close'}];
        },
        data: function () { return {html: this.options.html}; },
        actionClose: function () { this.close(); }
    });
});
