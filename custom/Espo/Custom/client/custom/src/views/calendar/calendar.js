/* global define */

/**
 * Calendario: alla creazione Appuntamento forza dateEnd = dateStart + 1h30
 * (lo slot click/drag del calendario è spesso 30m).
 * Apre il modal custom per assignedUsers + durata.
 */
define('custom:views/calendar/calendar', [
    'crm:views/calendar/calendar',
    'custom:helpers/appuntamento-duration',
], function (CalendarViewModule, AppuntamentoDurationHelper) {

    const Dep = CalendarViewModule.default || CalendarViewModule;
    const DEFAULT_DURATION_SECONDS = 5400;
    const EDIT_MODAL = 'custom:views/calendar/modals/edit';

    return Dep.extend({

        createEvent: function (values) {
            if (!values || typeof values !== 'object') {
                values = {};
            }

            // Forza durata 1h30 prima di aprire il modal (Appuntamento / default scope)
            const scope = values.scope || (this.options && this.options.scope) || null;
            const isAppuntamento = !scope || scope === 'Appuntamento';

            if (
                isAppuntamento &&
                !values.allDay &&
                values.dateStart &&
                !values.dateStartDate
            ) {
                const dateEnd = AppuntamentoDurationHelper.addSecondsToSystemDateTime(
                    this.getDateTime(),
                    values.dateStart,
                    DEFAULT_DURATION_SECONDS
                );

                if (dateEnd) {
                    values = Object.assign({}, values, {dateEnd: dateEnd});
                }
            }

            // Preferisci modal custom; se il core non espone createEvent overrideabile
            // con dialogView, monkey-patch createView solo per questo dialog.
            const originalCreateView = this.createView.bind(this);

            this.createView = (name, viewName, options, callback) => {
                if (
                    name === 'quickEdit' ||
                    name === 'dialog' ||
                    (typeof viewName === 'string' && viewName.indexOf('calendar/modals/edit') !== -1)
                ) {
                    viewName = EDIT_MODAL;

                    if (options && options.attributes && isAppuntamento && values.dateEnd) {
                        options = Object.assign({}, options, {
                            attributes: Object.assign({}, options.attributes, {
                                dateEnd: values.dateEnd,
                            }),
                            dateEnd: values.dateEnd,
                        });
                    } else if (options && values.dateEnd) {
                        options = Object.assign({}, options, {dateEnd: values.dateEnd});
                    }
                }

                return originalCreateView(name, viewName, options, callback);
            };

            try {
                return Dep.prototype.createEvent.call(this, values);
            } finally {
                this.createView = originalCreateView;
            }
        },
    });
});
