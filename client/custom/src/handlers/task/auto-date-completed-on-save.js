define('custom:handlers/task/auto-date-completed-on-save', [], function () {

    const STORAGE_FORMAT = 'YYYY-MM-DD HH:mm:ss';

    const nowStorageString = function () {
        if (typeof moment !== 'undefined' && moment.utc) {
            return moment.utc().format(STORAGE_FORMAT);
        }

        const d = new Date();
        const pad = n => String(n).padStart(2, '0');

        return (
            d.getUTCFullYear() + '-' +
            pad(d.getUTCMonth() + 1) + '-' +
            pad(d.getUTCDate()) + ' ' +
            pad(d.getUTCHours()) + ':' +
            pad(d.getUTCMinutes()) + ':' +
            pad(d.getUTCSeconds())
        );
    };

    return class {

        constructor(view) {
            this.view = view;
        }

        process() {
            if (this.view.scope !== 'Task' || !this.view.model || this.view.model._autoDateCompletedHook) {
                return;
            }

            this.view.model._autoDateCompletedHook = true;

            const model = this.view.model;
            const originalSave = model.save.bind(model);

            model.save = function (data, options) {
                data = data || {};

                const nextStatus = data.status || model.get('status');
                const nextDateCompleted = data.dateCompleted || model.get('dateCompleted');

                if (nextStatus === 'Completed' && !nextDateCompleted) {
                    const completedAt = nowStorageString();
                    data.dateCompleted = completedAt;
                    model.set('dateCompleted', completedAt, {silent: true});
                }

                return originalSave(data, options);
            };
        }
    };
});
