/* global define */

define('custom:views/calendar/calendar', ['crm:views/calendar/calendar'], function (Dep) {

    return Dep.extend({

        createEvent: function (values) {
            const originalCreateView = this.createView.bind(this);

            this.createView = function (name, viewName, options) {
                if (name === 'dialog' && viewName === 'crm:views/calendar/modals/edit') {
                    viewName = 'custom:views/calendar/modals/edit';
                }

                return originalCreateView(name, viewName, options);
            };

            const result = Dep.prototype.createEvent.call(this, values);

            return Promise.resolve(result).finally(() => {
                this.createView = originalCreateView;
            });
        },
    });
});
